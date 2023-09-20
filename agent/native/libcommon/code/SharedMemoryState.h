

#include <boost/interprocess/anonymous_shared_memory.hpp>
#include <boost/interprocess/mapped_region.hpp>
#include <boost/interprocess/sync/interprocess_upgradable_mutex.hpp>
#include <boost/interprocess/sync/scoped_lock.hpp>
#include <boost/interprocess/sync/sharable_lock.hpp>

namespace elasticapm::php {

class SharedMemoryState {
public:
    struct SharedData {
        boost::interprocess::interprocess_upgradable_mutex mutex;
        bool oneTimeTaskAmongWorkersExecuted = false;
    };

    bool shouldExecuteOneTimeTaskAmongWorkers() {
        {
            boost::interprocess::sharable_lock< decltype( SharedData::mutex ) > lock( data_->mutex );
            if ( data_->oneTimeTaskAmongWorkersExecuted )
            {
                return false;
            }
        }

        boost::interprocess::scoped_lock< decltype( SharedData::mutex ) > ulock( data_->mutex );
        if ( data_->oneTimeTaskAmongWorkersExecuted )
        {
            return false;
        }
        data_->oneTimeTaskAmongWorkersExecuted = true;
        return true;
    }


protected:
    boost::interprocess::mapped_region region_{ boost::interprocess::anonymous_shared_memory( sizeof( SharedData ) ) };
    SharedData* data_{ new (region_.get_address()) SharedData };
};

}